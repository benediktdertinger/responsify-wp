<?php
abstract class Create_Responsive_image
{
	protected $image_sizes;
	protected $id;
	protected $images;
	protected $settings;

	public function __construct( $id, $settings )
	{
		$this->id = $id;
		$this->settings = $settings;

		// 1. Hämta bildstorlekar
		$this->image_sizes = $this->get_image_sizes();

		$this->images = $this->get_images( $this->image_sizes );

		// 3. Sortera bilderna i storleksordning
		$this->images = $this->order_images( $this->images );

        if ( isset($this->settings['retina']) && $this->settings['retina'] ) {
            $this->group_highres();
        }

		// 4. Räkna ut vilka media queries bilderna ska ha
		$user_media_queries = (isset($settings['media_queries'])) ? $settings['media_queries'] : null;
		$media_queries = new Media_Queries( $this->images, $user_media_queries );
		$this->images = $media_queries->set();
	}

    /**
     * Finds images in the selected sizes.
     *
     * @param $sizes
     * @return array
     */
    public function get_images( $sizes )
	{
		$images = array();
		$image_srcs = array();

        $notBiggerThan = (isset($this->settings['notBiggerThan'])) ? $this->settings['notBiggerThan'] : null;

		foreach ( $sizes as $size ) {
			$image = $this->get_image($size);
			if ( !in_array($image[0], $image_srcs) ) {
				array_push($images, array(
					'src' => $image[0],
					'size' => $size,
					'width' => $image[1],
					'height' => $image[2]
				));
				array_push($image_srcs, $image[0]);
			}
			if (isset($notBiggerThan) && ($image[0] == $notBiggerThan)) break;
		}
		return $images;
	}
    
    protected function group_highres() {
        $retina_image_indexes = array();
        for ($i=0; $i < count($this->images); $i++) { 
            if ( strpos($this->images[$i]['size'], '@') ) {
                $retina_image_indexes[] = $i;
                continue;
            }
            $possible_retina_image_name = $this->images[$i]['size'] . '@';
            foreach ($this->images as $image) {
                if ( substr($image['size'], 0, strlen($possible_retina_image_name)) == $possible_retina_image_name ) {
                    $density = substr($image['size'], (strpos($image['size'], '@')+1));
                    $this->images[$i]['highres'][$density] = $image;
                }
            }
        }
        for ($i=0; $i < count($retina_image_indexes); $i++) { 
            unset($this->images[$retina_image_indexes[$i]]);
        }
    }

    /**
     * Finds a single image in the selected size
     *
     * @param $size
     * @return array
     */
    protected function get_image( $size )
	{
		return wp_get_attachment_image_src( $this->id, $size );
	}

    /**
     * Orders the array of images based on width.
     *
     * @param $images
     * @return array
     */
    protected function order_images( $images )
	{
		usort($images, function($img1, $img2) {
			return $img1['width'] < $img2['width'] ? -1 : 1;
		});
        return $images;
	}

    /**
     * Finds and returns all available image sizes.
     *
     * @return array
     */
    protected function get_image_sizes()
	{
        if ( isset($this->settings['sizes']) ) {
            if ( isset($this->settings['retina']) ) {
                $this->settings['sizes'] = $this->add_retina_sizes( $this->settings['sizes'] );
            }
            return $this->settings['sizes'];
        }

        $selected_sizes = get_option( 'selected_sizes' );
        $image_sizes = ( $selected_sizes ) ? array_keys($selected_sizes) : get_intermediate_image_sizes() ;
        if ( !in_array('full', $image_sizes) ) {
            array_push($image_sizes, 'full');
        }
        return $image_sizes;
	}

    protected function add_retina_sizes( $image_sizes )
    {
        $density = $this->settings['retina'];
        foreach ( $image_sizes as $image_size ) {
            array_push($image_sizes, $image_size.'@'.$density);
        }                
        return $image_sizes;
    }

    /**
     * Gets a meta value.
     *
     * @param $meta
     * @return array
     */
    protected function get_image_meta( $meta )
    {
        return get_post_meta($this->id, '_wp_attachment_image_' . $meta, true);
    }

    /**
     * Makes a string with all attributes.
     *
     * @param $attribute_array
     * @return string
     */
    protected function create_attributes( $attribute_array )
    {
        $attributes = '';
        foreach ($attribute_array as $attribute => $value) {
            $attributes .= $attribute . '="' . $value . '" ';
        }
        // Removes the extra space after the last attribute
        return substr($attributes, 0, -1);
    }


}